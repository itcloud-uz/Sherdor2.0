<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Models\CashRegister;
use App\Models\TransactionCategory;
use App\Models\Partner;
use App\Models\Building;
use App\Models\Worker;
use App\Models\Supplier;
use App\Models\Brigade;
use App\Models\MachineOwner;
use Illuminate\Http\Request;

class BotApiController extends Controller
{
    /**
     * 1. Verify user by Telegram Chat ID
     */
    public function verifyUser(Request $request)
    {
        $request->validate([
            'chat_id' => 'required|string',
        ]);

        $user = User::where('telegram_chat_id', $request->chat_id)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found for this chat ID.'], 404);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'roles' => $user->getRoleNames(),
                'balance' => $user->balance,
            ]
        ]);
    }


    /**
     * 1b. Get metadata (registers, categories, contracts, partners, buildings, workers, suppliers, brigades, machine owners)
     */
    public function getMetadata(Request $request)
    {
        $registers = CashRegister::where('is_active', true)->get(['id', 'name', 'currency']);
        $categories = TransactionCategory::get(['id', 'name', 'type']);
        
        $contracts = \App\Models\Contract::with(['client:id,first_name,last_name', 'unit:id,unit_number'])
            ->where('status', '!=', 'completed')
            ->latest()
            ->get(['id', 'client_id', 'unit_id', 'status']);
            
        $partners = Partner::with('user:id,name')->get(['id', 'user_id']);
        $partnersMapped = $partners->map(function($p) {
            return [
                'id' => $p->id,
                'name' => $p->user ? $p->user->name : "Partner #{$p->id}"
            ];
        });

        $buildings = Building::get(['id', 'name']);
        $workers = Worker::get(['id', 'name']);
        $suppliers = Supplier::get(['id', 'name']);
        $brigades = Brigade::get(['id', 'name']);
        $machineOwners = MachineOwner::get(['id', 'name']);

        return response()->json([
            'registers' => $registers,
            'categories' => $categories,
            'contracts' => $contracts,
            'partners' => $partnersMapped,
            'buildings' => $buildings,
            'workers' => $workers,
            'suppliers' => $suppliers,
            'brigades' => $brigades,
            'machine_owners' => $machineOwners,
        ]);
    }

    /**
     * 2. Create a new transaction from Telegram
     */
    public function createTransaction(Request $request)
    {
        $request->validate([
            'chat_id' => 'required|string',
            'type' => 'required|in:income,expense',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string',
            'payment_method' => 'required|in:cash,card,bank_transfer',
            'account_id' => 'required|exists:cash_registers,id',
            'category_id' => 'required|exists:transaction_categories,id',
            'contract_id' => 'nullable|exists:contracts,id',
            'client_id' => 'nullable|exists:clients,id',
            'partner_id' => 'nullable|exists:partners,id',
            'worker_id' => 'nullable|exists:workers,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'building_id' => 'nullable|exists:buildings,id',
            'target_type' => 'nullable|string|in:partner,supplier,client,worker,brigade,machine_owner',
            'target_id' => 'nullable|integer',
            'notes' => 'nullable|string',
            'description' => 'nullable|string',
            'source' => 'nullable|string',
        ]);

        $user = User::where('telegram_chat_id', $request->chat_id)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        $register = CashRegister::findOrFail($request->account_id);
        
        // Object Lock Check - Relaxed
        $buildingId = $request->building_id;
        if (empty($buildingId) && $register->building_id) {
            $buildingId = $register->building_id;
        }

        $currency = $request->currency ?? $register->currency ?? 'UZS';
        
        // Consensus check for large expenses
        $requiresConsensus = false;
        $isCreditRegister = stripos($register->name, 'kredit') !== false;
        if ($request->type === 'expense' && $request->amount >= 100000000 && !$isCreditRegister) {
            $requiresConsensus = true;
        }

        // Map client_id if contract_id is given
        $clientId = $request->client_id;
        if (empty($clientId) && !empty($request->contract_id)) {
            $contract = \App\Models\Contract::find($request->contract_id);
            if ($contract) {
                $clientId = $contract->client_id;
            }
        }

        // Polymorphic target mapping
        $targetType = $request->target_type;
        $targetId = $request->target_id;
        
        if (empty($targetType) && empty($targetId)) {
            if ($request->partner_id) {
                $targetType = 'partner';
                $targetId = $request->partner_id;
            } elseif ($request->supplier_id) {
                $targetType = 'supplier';
                $targetId = $request->supplier_id;
            } elseif ($clientId) {
                $targetType = 'client';
                $targetId = $clientId;
            } elseif ($request->worker_id) {
                $targetType = 'worker';
                $targetId = $request->worker_id;
            }
        }

        $notes = $request->notes ?? $request->description;

        $transaction = Transaction::create([
            'type' => $request->type,
            'amount' => $request->amount,
            'currency' => $currency,
            'payment_method' => $request->payment_method,
            'account_id' => $request->account_id,
            'category_id' => $request->category_id,
            'contract_id' => $request->contract_id,
            'client_id' => $clientId,
            'partner_id' => $request->partner_id,
            'worker_id' => $request->worker_id,
            'supplier_id' => $request->supplier_id,
            'building_id' => $buildingId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'notes' => $notes,
            'created_by' => $user->id,
            'status' => 'pending',
            'source' => $request->source ?? 'telegram',
            'transaction_date' => now()->toDateString(),
            'requires_consensus' => $requiresConsensus,
            'exchange_rate' => 1.0,
            'converted_amount' => $request->amount,
        ]);

        if ($requiresConsensus) {
            $consensusRequest = \App\Models\ConsensusRequest::create([
                'transaction_id' => $transaction->id,
                'status' => 'pending'
            ]);

            $partners = Partner::all();
            foreach ($partners as $partner) {
                \App\Models\ConsensusVote::create([
                    'consensus_request_id' => $consensusRequest->id,
                    'partner_id' => $partner->id,
                    'vote' => 'pending'
                ]);
            }
        }

        return response()->json([
            'message' => 'Transaction created successfully and is pending approval.',
            'transaction_id' => $transaction->id
        ]);
    }

    /**
     * 3. Get list of pending transactions
     */
    public function getPendingTransactions(Request $request)
    {
        $transactions = Transaction::with(['creator:id,name', 'category'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'transactions' => $transactions
        ]);
    }

    /**
     * 4. Approve transaction
     */
    public function approveTransaction(Request $request)
    {
        $request->validate([
            'chat_id' => 'required|string',
            'transaction_id' => 'required|integer|exists:transactions,id',
        ]);

        $user = User::where('telegram_chat_id', $request->chat_id)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        if (!$user->hasRole('admin') && !$user->hasRole('super-admin')) {
            return response()->json(['error' => 'Permission denied. Only admins can approve.'], 403);
        }

        $transaction = Transaction::findOrFail($request->transaction_id);

        if ($transaction->status !== 'pending') {
            return response()->json(['error' => 'Transaction is not pending.'], 400);
        }

        auth()->login($user);

        // Call the approveTransaction method of FinanceController passing both $request and $transaction
        $response = app(\App\Http\Controllers\Admin\FinanceController::class)->approveTransaction($request, $transaction);

        auth()->logout();

        if ($response instanceof \Illuminate\Http\JsonResponse) {
            return $response;
        }

        return response()->json([
            'message' => 'Transaction approved successfully.',
            'transaction_id' => $transaction->id
        ]);
    }
}
