<?php

namespace App\Http\Controllers;

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
use App\Models\Contract;
use App\Models\ConsensusRequest;
use App\Models\ConsensusVote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class TelegramMiniAppController extends Controller
{
    /**
     * Render the Telegram Mini App HTML view.
     */
    public function index(Request $request)
    {
        return view('miniapp.transactions');
    }

    /**
     * Initialize Mini App data for the current Telegram user.
     */
    public function init(Request $request)
    {
        $chatId = $request->input('chat_id');

        if (empty($chatId)) {
            return response()->json([
                'ok' => false,
                'error' => 'Telegram Chat ID aniqlanmadi. Iltimos, Telegram orqali kiring.'
            ], 400);
        }

        // Look up user by telegram_chat_id
        $user = User::where('telegram_chat_id', (string) $chatId)->first();

        if (!$user) {
            return response()->json([
                'ok' => false,
                'error' => "Sizning Telegram hisobingiz (ID: {$chatId}) Sher-Dor ERP tizimida xodim sifatida ro'yxatdan o'tmagan. Iltimos, adminga murojaat qiling."
            ], 403);
        }

        // Check if user has admin/partner permissions
        $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->toArray() : [];
        $isAdminOrPartner = in_array('Super_Admin', $roles) || in_array('Admin', $roles) || in_array('Partner', $roles);

        // Fetch Cash Registers
        $registers = CashRegister::where('is_active', true)
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'name' => $r->name,
                    'currency' => $r->currency,
                    'balance_uzs' => (float) $r->balance_uzs,
                    'balance_usd' => (float) $r->balance_usd,
                ];
            });

        // Fetch Categories
        $categories = TransactionCategory::orderBy('name')
            ->get(['id', 'name', 'type']);

        // Fetch Active Contracts
        $contracts = Contract::with(['client:id,first_name,last_name,phone', 'unit:id,unit_number'])
            ->where('status', '!=', 'completed')
            ->latest()
            ->limit(100)
            ->get()
            ->map(function ($c) {
                $clientName = $c->client ? trim("{$c->client->first_name} {$c->client->last_name}") : "Mijoz #{$c->client_id}";
                $unitNum = $c->unit ? $c->unit->unit_number : ($c->unit_id ? "#{$c->unit_id}" : "-");
                return [
                    'id' => $c->id,
                    'client_id' => $c->client_id,
                    'client_name' => $clientName,
                    'contract_number' => $c->contract_number ?: "№{$c->id}",
                    'unit_number' => $unitNum,
                    'label' => "{$c->contract_number} — {$clientName} ({$unitNum}-uy)"
                ];
            });

        // Fetch Partners
        $partners = Partner::with('user:id,name')->get()->map(function ($p) {
            return [
                'id' => $p->id,
                'name' => $p->user ? $p->user->name : "Hamkor #{$p->id}"
            ];
        });

        // Fetch Buildings
        $buildings = Building::get(['id', 'name']);

        // Fetch Suppliers
        $suppliers = Supplier::get(['id', 'name']);

        // Fetch Workers
        $workers = Worker::get(['id', 'name']);

        // Fetch Brigades
        $brigades = Brigade::get(['id', 'name']);

        // Fetch Machine Owners
        $machineOwners = MachineOwner::get(['id', 'name']);

        // Fetch Recent Transactions
        $recentTransactions = Transaction::with(['category:id,name', 'cashRegister:id,name', 'creator:id,name'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'type' => $t->type,
                    'amount' => $t->amount,
                    'currency' => $t->currency,
                    'status' => $t->status,
                    'payment_method' => $t->payment_method,
                    'category' => $t->category ? $t->category->name : ($t->category_display ?: '-'),
                    'register' => $t->cashRegister ? $t->cashRegister->name : '-',
                    'date' => $t->transaction_date ?: $t->created_at->format('Y-m-d'),
                    'notes' => $t->notes ?: $t->description,
                    'creator' => $t->creator ? $t->creator->name : '-'
                ];
            });

        // Count pending approvals
        $pendingCount = Transaction::where('status', 'pending')->count();

        return response()->json([
            'ok' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'roles' => $roles,
                'is_admin' => $isAdminOrPartner
            ],
            'registers' => $registers,
            'categories' => $categories,
            'contracts' => $contracts,
            'partners' => $partners,
            'buildings' => $buildings,
            'suppliers' => $suppliers,
            'workers' => $workers,
            'brigades' => $brigades,
            'machine_owners' => $machineOwners,
            'recent_transactions' => $recentTransactions,
            'pending_count' => $pendingCount
        ]);
    }

    /**
     * Store new transaction created via Mini App.
     */
    public function storeTransaction(Request $request)
    {
        $chatId = $request->input('chat_id');
        if (empty($chatId)) {
            return response()->json(['ok' => false, 'error' => 'Telegram Chat ID mavjud emas'], 400);
        }

        $user = User::where('telegram_chat_id', (string) $chatId)->first();
        if (!$user) {
            return response()->json(['ok' => false, 'error' => 'Foydalanuvchi topilmadi'], 403);
        }

        $validated = $request->validate([
            'type' => 'required|in:income,expense',
            'amount' => 'required|numeric|min:1',
            'currency' => 'nullable|string|in:UZS,USD',
            'payment_method' => 'required|in:cash,card,bank_transfer',
            'account_id' => 'required|exists:cash_registers,id',
            'category_id' => 'required|exists:transaction_categories,id',
            'contract_id' => 'nullable|exists:contracts,id',
            'client_id' => 'nullable|exists:clients,id',
            'partner_id' => 'nullable|exists:partners,id',
            'worker_id' => 'nullable|exists:workers,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'building_id' => 'nullable|exists:buildings,id',
            'brigade_id' => 'nullable|exists:brigades,id',
            'machine_owner_id' => 'nullable|exists:machine_owners,id',
            'target_type' => 'nullable|string',
            'target_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:1000',
            'transaction_date' => 'nullable|date',
            'attachment' => 'nullable|image|max:10240', // Max 10MB
        ]);

        $register = CashRegister::findOrFail($validated['account_id']);
        $buildingId = $validated['building_id'] ?? $register->building_id;
        $currency = $validated['currency'] ?? ($register->currency ?? 'UZS');

        // Consensus logic for large expenses
        $requiresConsensus = false;
        $isCreditRegister = stripos($register->name, 'kredit') !== false;
        if ($validated['type'] === 'expense' && $validated['amount'] >= 100000000 && !$isCreditRegister) {
            $requiresConsensus = true;
        }

        // Target mapping
        $targetType = $validated['target_type'] ?? null;
        $targetId = $validated['target_id'] ?? null;
        $clientId = $validated['client_id'] ?? null;

        if (!empty($validated['contract_id']) && empty($clientId)) {
            $contract = Contract::find($validated['contract_id']);
            if ($contract) {
                $clientId = $contract->client_id;
                $targetType = 'client';
                $targetId = $clientId;
            }
        } elseif (!empty($validated['partner_id'])) {
            $targetType = 'partner';
            $targetId = $validated['partner_id'];
        } elseif (!empty($validated['supplier_id'])) {
            $targetType = 'supplier';
            $targetId = $validated['supplier_id'];
        } elseif (!empty($validated['worker_id'])) {
            $targetType = 'worker';
            $targetId = $validated['worker_id'];
        } elseif (!empty($validated['brigade_id'])) {
            $targetType = 'brigade';
            $targetId = $validated['brigade_id'];
        } elseif (!empty($validated['machine_owner_id'])) {
            $targetType = 'machine_owner';
            $targetId = $validated['machine_owner_id'];
        }

        // Handle attachment file upload
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('transactions/attachments', 'public');
        }

        $transaction = Transaction::create([
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'currency' => $currency,
            'payment_method' => $validated['payment_method'],
            'account_id' => $validated['account_id'],
            'category_id' => $validated['category_id'],
            'contract_id' => $validated['contract_id'] ?? null,
            'client_id' => $clientId,
            'partner_id' => $validated['partner_id'] ?? null,
            'worker_id' => $validated['worker_id'] ?? null,
            'supplier_id' => $validated['supplier_id'] ?? null,
            'building_id' => $buildingId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'notes' => $validated['notes'] ?? null,
            'description' => $validated['notes'] ?? null,
            'attachment_path' => $attachmentPath,
            'created_by' => $user->id,
            'status' => 'pending',
            'source' => 'telegram_miniapp',
            'transaction_date' => $validated['transaction_date'] ?? now()->toDateString(),
            'requires_consensus' => $requiresConsensus,
        ]);

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

        return response()->json([
            'ok' => true,
            'message' => 'Tranzaksiya muvaffaqiyatli qabul qilindi va tasdiqlash uchun yuborildi!',
            'transaction_id' => $transaction->id
        ]);
    }

    /**
     * Get pending transactions for approval.
     */
    public function getPending(Request $request)
    {
        $chatId = $request->input('chat_id');
        $user = User::where('telegram_chat_id', (string) $chatId)->first();
        if (!$user) {
            return response()->json(['ok' => false, 'error' => 'Ruxsat yo\'q'], 403);
        }

        $transactions = Transaction::with(['category:id,name', 'cashRegister:id,name', 'creator:id,name'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'type' => $t->type,
                    'amount' => $t->amount,
                    'currency' => $t->currency,
                    'payment_method' => $t->payment_method,
                    'category' => $t->category ? $t->category->name : ($t->category_display ?: '-'),
                    'register' => $t->cashRegister ? $t->cashRegister->name : '-',
                    'date' => $t->transaction_date ?: $t->created_at->format('Y-m-d'),
                    'notes' => $t->notes ?: $t->description,
                    'creator' => $t->creator ? $t->creator->name : '-',
                    'attachment_url' => $t->attachment_path ? asset("storage/{$t->attachment_path}") : null
                ];
            });

        return response()->json([
            'ok' => true,
            'transactions' => $transactions
        ]);
    }

    /**
     * Approve or reject a transaction via Mini App.
     */
    public function processApproval(Request $request)
    {
        $chatId = $request->input('chat_id');
        $user = User::where('telegram_chat_id', (string) $chatId)->first();
        if (!$user) {
            return response()->json(['ok' => false, 'error' => 'Foydalanuvchi topilmadi'], 403);
        }

        $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->toArray() : [];
        $isAdminOrPartner = in_array('Super_Admin', $roles) || in_array('Admin', $roles) || in_array('Partner', $roles);

        if (!$isAdminOrPartner) {
            return response()->json(['ok' => false, 'error' => 'Sizda tranzaksiyani tasdiqlash huquqi yo\'q'], 403);
        }

        $request->validate([
            'transaction_id' => 'required|exists:transactions,id',
            'action' => 'required|in:approve,reject'
        ]);

        $transaction = Transaction::findOrFail($request->transaction_id);

        if ($request->action === 'approve') {
            if ($transaction->status !== 'pending') {
                return response()->json(['ok' => false, 'error' => "Tranzaksiya allaqachon ko'rib chiqilgan ({$transaction->status})."], 400);
            }

            auth()->login($user);
            try {
                $response = app(\App\Http\Controllers\Admin\FinanceController::class)->approveTransaction($request, $transaction);
                if ($response instanceof \Illuminate\Http\JsonResponse) {
                    $responseData = $response->getData(true);
                    if (isset($responseData['error'])) {
                        return response()->json(['ok' => false, 'error' => $responseData['error']], 400);
                    }
                }
            } finally {
                auth()->logout();
            }

            return response()->json([
                'ok' => true,
                'message' => "Tranzaksiya №{$transaction->id} muvaffaqiyatli tasdiqlandi!"
            ]);
        } else {
            $transaction->status = 'rejected';
            $transaction->approved_by = $user->id;
            $transaction->save();

            return response()->json([
                'ok' => true,
                'message' => "Tranzaksiya №{$transaction->id} rad etildi."
            ]);
        }
    }
}
